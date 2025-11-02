// WhimsicalFrog Core – DOMUtils (ES module)
// Migrated from legacy js/utils.js to modern module syntax.
// Provides various DOM helper utilities used across the site.

export class DOMUtils {
  static setContent(element, content, showLoading = false) {
    if (!element) return;
    if (showLoading) {
      element.innerHTML = '<div class="text-center text-gray-500 py-4">Loading...</div>';
      setTimeout(() => {
        element.innerHTML = content;
      }, 100);
    } else {
      element.innerHTML = content;
    }
  }

  static createLoadingSpinner(message = 'Loading...') {
    return `\n            <div class="flex items-center justify-center py-4">\n                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mr-3"></div>\n                <span class="text-gray-600">${message}</span>\n            </div>\n        `;
  }

  static createErrorMessage(message) {
    return `\n            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">\n                <div class="flex items-center">\n                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">\n                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>\n                    </svg>\n                    ${message}\n                </div>\n            </div>\n        `;
  }

  static createSuccessMessage(message) {
    return `\n            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">\n                <div class="flex items-center">\n                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">\n                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>\n                    </svg>\n                    ${message}\n                </div>\n            </div>\n        `;
  }

  static showToast(message, type = 'info', duration = 3000) {
    const toastId = 'toast-' + Date.now();
    const colors = {
      success: 'bg-green-500',
      error: 'bg-red-500',
      info: 'bg-blue-500'
    };

    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.classList.remove('translate-x-full');
    }, 100);

    setTimeout(() => {
      toast.classList.add('translate-x-full');
      setTimeout(() => {
        document.getElementById(toastId)?.remove();
      }, 300);
    }, duration);
  }

  static debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func(...args), wait);
    };
  }

  static formatCurrency(value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
  }

  static escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  static async confirm(message, title = 'Confirm') {
    try {
      if (typeof window !== 'undefined' && typeof window.showConfirmationModal === 'function') {
        const ok = await window.showConfirmationModal({
          title,
          message,
          confirmText: 'Confirm',
          cancelText: 'Cancel',
          icon: '⚠️',
          iconType: 'warning',
          confirmStyle: 'confirm'
        });
        return !!ok;
      }
    } catch (_) {}
    return new Promise(resolve => {
      const modal = document.createElement('div');
      modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
      modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">${DOMUtils.escapeHtml(title)}</h3>
                    <p class="text-gray-600 mb-6">${DOMUtils.escapeHtml(message)}</p>
                    <div class="flex justify-end space-x-3">
                        <button class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" data-action="cancel">Cancel</button>
                        <button class="px-4 py-2 bg-red-500 text-white hover:bg-red-600 rounded" data-action="confirm">Confirm</button>
                    </div>
                </div>
            `;
      document.body.appendChild(modal);
      modal.addEventListener('click', e => {
        if (e.target.dataset.action === 'confirm') {
          modal.remove();
          resolve(true);
        } else if (e.target.dataset.action === 'cancel' || e.target === modal) {
          modal.remove();
          resolve(false);
        }
      });
    });
  }
}

// Convenience global exposure for legacy code while in transition
if (typeof window !== 'undefined') {
  window.DOMUtils = DOMUtils;
  window.debounce = DOMUtils.debounce;
  window.formatCurrency = DOMUtils.formatCurrency;
  window.escapeHtml = DOMUtils.escapeHtml;
  window.showToast = DOMUtils.showToast;
  window.confirmDialog = DOMUtils.confirm;
}

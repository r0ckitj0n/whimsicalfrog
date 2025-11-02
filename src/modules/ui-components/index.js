/**
 * UI Components
 * Shared UI components and utilities
 */

export class UIComponents {
  constructor() {
    this.components = new Map();
    this.initialized = false;
  }

  /**
   * Initialize UI components
   */
  init() {
    if (this.initialized) return;

    this.setupGlobalComponents();
    this.initialized = true;

    console.log('[UIComponents] Initialized');
  }

  /**
   * Create modal component
   * @param {Object} options - Modal options
   * @returns {Object} Modal instance
   */
  createModal(options = {}) {
    const modalId = options.id || this.generateId('modal');

    const modal = {
      id: modalId,
      element: null,
      options: {
        title: options.title || 'Modal',
        content: options.content || '',
        size: options.size || 'medium', // small, medium, large, wide
        closable: options.closable !== false,
        backdropClose: options.backdropClose !== false,
        ...options
      },

      show() {
        if (!this.element) this.render();

        if (this.options.variant === 'admin') {
          this.element.classList.remove('hidden');
          this.element.classList.add('show');
          this.element.setAttribute('aria-hidden', 'false');
        } else {
          this.element.classList.add('show');
        }
        document.body.classList.add('modal-open');

        const focusable = this.element.querySelector('[autofocus], input, button, [tabindex="0"]');
        if (focusable) focusable.focus();

        this.dispatchEvent('show');
      },

      hide() {
        if (!this.element) return;

        if (this.options.variant === 'admin') {
          this.element.classList.add('hidden');
          this.element.classList.remove('show');
          this.element.setAttribute('aria-hidden', 'true');
        } else {
          this.element.classList.remove('show');
        }
        document.body.classList.remove('modal-open');

        this.dispatchEvent('hide');
      },

      destroy() {
        if (this.element) {
          this.element.remove();
          this.element = null;
        }
        this.dispatchEvent('destroy');
      },

      setTitle(title) {
        const titleElement = this.element?.querySelector('.modal-title, .admin-card-title');
        if (titleElement) {
          titleElement.textContent = title;
        }
      },

      setContent(content) {
        const contentElement = this.element?.querySelector('.modal-body');
        if (contentElement) {
          if (typeof content === 'string') {
            contentElement.innerHTML = content;
          } else {
            contentElement.innerHTML = '';
            contentElement.appendChild(content);
          }
        }
      },

      render() {
        const existing = document.getElementById(modalId);
        if (existing) {
          this.element = existing;
          return;
        }

        const modalElement = document.createElement('div');
        modalElement.id = modalId;

        if (this.options.variant === 'admin') {
          // Admin-styled modal overlay and container with brand close X
          modalElement.className = 'admin-modal-overlay hidden';
          modalElement.setAttribute('aria-hidden', 'true');
          modalElement.setAttribute('role', 'dialog');
          modalElement.setAttribute('aria-modal', 'true');
          modalElement.setAttribute('tabindex', '-1');
          modalElement.setAttribute('aria-labelledby', `${modalId}_title`);

          const adminSize = this.options.adminSize || 'admin-modal--lg';
          modalElement.innerHTML = `
            <div class="admin-modal admin-modal-content ${adminSize} admin-modal--actions-in-header">
              <div class="modal-header">
                <h2 id="${modalId}_title" class="admin-card-title">${this.options.title}</h2>
                ${this.options.headerActions ? `<div class="modal-header-actions">${this.options.headerActions}</div>` : ''}
                ${this.options.closable !== false ? '<button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>' : ''}
              </div>
              <div class="modal-body">
                ${this.options.content}
              </div>
              ${this.options.footer ? `
                <div class="modal-footer">
                  ${this.options.footer}
                </div>
              ` : ''}
            </div>
          `;
        } else {
          // Default generic modal
          modalElement.className = `modal-overlay ${this.options.size ? `modal-${this.options.size}` : ''}`;
          modalElement.innerHTML = `
            <div class="modal-container">
              <div class="modal-header">
                <h3 class="modal-title">${this.options.title}</h3>
                ${this.options.closable ? '<button class="modal-close btn btn-icon btn-icon--close" aria-label="Close"></button>' : ''}
              </div>
              <div class="modal-body">
                ${this.options.content}
              </div>
              ${this.options.footer ? `
                <div class="modal-footer">
                  ${this.options.footer}
                </div>
              ` : ''}
            </div>
          `;
        }

        document.body.appendChild(modalElement);
        this.element = modalElement;

        this.bindEvents();
      },

      bindEvents() {
        // Close button (support admin and default variants)
        const closeBtn = this.element.querySelector('.admin-modal-close, .modal-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', () => this.hide());
        }

        // Backdrop close
        if (this.options.backdropClose) {
          this.element.addEventListener('click', (e) => {
            if (e.target === this.element) {
              this.hide();
            }
          });
        }

        // Escape key
        const handleEscape = (e) => {
          if (e.key === 'Escape') {
            this.hide();
          }
        };

        this.element.addEventListener('show', () => {
          document.addEventListener('keydown', handleEscape);
        });

        this.element.addEventListener('hide', () => {
          document.removeEventListener('keydown', handleEscape);
        });
      },

      dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`modal:${eventName}`, { detail });
        this.element.dispatchEvent(event);
      }
    };

    this.components.set(modalId, modal);
    return modal;
  }

  /**
   * Convenience: create an Admin-styled modal with brand close X
   * @param {Object} options
   * @returns {Object} Modal instance
   */
  createAdminModal(options = {}) {
    const o = options || {};
    return this.createModal({
      ...o,
      variant: 'admin',
      adminSize: o.adminSize || 'admin-modal--lg',
      headerActions: o.headerActions ?? o.actions ?? ''
    });
  }

  /**
   * Create toast notification
   * @param {string} message - Toast message
   * @param {string} type - Toast type (success, error, warning, info)
   * @param {Object} options - Toast options
   * @returns {Object} Toast instance
   */
  createToast(message, type = 'info', options = {}) {
    const toastId = this.generateId('toast');

    const toast = {
      id: toastId,
      element: null,
      options: {
        duration: options.duration || 5000,
        position: options.position || 'top-right', // top-right, top-left, bottom-right, bottom-left
        closable: options.closable !== false,
        ...options
      },

      show() {
        if (!this.element) this.render();

        this.element.classList.add('show');

        if (this.options.duration > 0) {
          setTimeout(() => {
            this.hide();
          }, this.options.duration);
        }

        this.dispatchEvent('show');
      },

      hide() {
        if (!this.element) return;

        this.element.classList.remove('show');
        setTimeout(() => {
          this.destroy();
        }, 300); // CSS transition duration

        this.dispatchEvent('hide');
      },

      destroy() {
        if (this.element) {
          this.element.remove();
          this.element = null;
        }
        this.dispatchEvent('destroy');
      },

      render() {
        const container = this.getOrCreateContainer();
        const toastElement = document.createElement('div');
        toastElement.className = `toast toast-${type} ${this.options.closable ? 'toast-closable' : ''}`;
        toastElement.innerHTML = `
          <div class="toast-content">
            <span class="toast-message">${message}</span>
            ${this.options.closable ? '<button class="toast-close">&times;</button>' : ''}
          </div>
        `;

        container.appendChild(toastElement);
        this.element = toastElement;

        this.bindEvents();
      },

      bindEvents() {
        const closeBtn = this.element.querySelector('.toast-close');
        if (closeBtn) {
          closeBtn.addEventListener('click', () => this.hide());
        }
      },

      getOrCreateContainer() {
        const position = this.options.position;
        let container = document.querySelector(`.toast-container[data-position="${position}"]`);

        if (!container) {
          container = document.createElement('div');
          container.className = `toast-container ${position}`;
          container.setAttribute('data-position', position);
          document.body.appendChild(container);
        }

        return container;
      },

      dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`toast:${eventName}`, { detail });
        document.dispatchEvent(event);
      }
    };

    this.components.set(toastId, toast);
    return toast;
  }

  /**
   * Create loading spinner
   * @param {Object} options - Spinner options
   * @returns {Object} Spinner instance
   */
  createSpinner(options = {}) {
    const spinnerId = this.generateId('spinner');

    const spinner = {
      id: spinnerId,
      element: null,
      options: {
        size: options.size || 'medium', // small, medium, large
        color: options.color || 'primary',
        overlay: options.overlay !== false,
        ...options
      },

      show() {
        if (!this.element) this.render();
        this.element.classList.add('show');
        this.element.classList.remove('hide');
        this.dispatchEvent('show');
      },

      hide() {
        if (!this.element) return;
        this.element.classList.add('hide');
        this.element.classList.remove('show');
        this.dispatchEvent('hide');
      },

      destroy() {
        if (this.element) {
          this.element.remove();
          this.element = null;
        }
        this.dispatchEvent('destroy');
      },

      render() {
        const spinnerElement = document.createElement('div');
        spinnerElement.className = `spinner spinner-${this.options.size} spinner-${this.options.color} ${this.options.overlay ? 'spinner-overlay' : ''}`;
        spinnerElement.innerHTML = `
          <div class="spinner-inner">
            <div class="spinner-circle"></div>
            <div class="spinner-circle"></div>
            <div class="spinner-circle"></div>
          </div>
          ${this.options.text ? `<div class="spinner-text">${this.options.text}</div>` : ''}
        `;

        if (this.options.target) {
          this.options.target.appendChild(spinnerElement);
        } else {
          document.body.appendChild(spinnerElement);
        }

        this.element = spinnerElement;
      },

      setText(text) {
        let textElement = this.element?.querySelector('.spinner-text');
        if (!textElement) {
          textElement = document.createElement('div');
          textElement.className = 'spinner-text';
          this.element.appendChild(textElement);
        }
        textElement.textContent = text;
      },

      dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`spinner:${eventName}`, { detail });
        document.dispatchEvent(event);
      }
    };

    this.components.set(spinnerId, spinner);
    return spinner;
  }

  /**
   * Create dropdown component
   * @param {Object} options - Dropdown options
   * @returns {Object} Dropdown instance
   */
  createDropdown(options = {}) {
    const dropdownId = this.generateId('dropdown');

    const dropdown = {
      id: dropdownId,
      element: null,
      options: {
        items: options.items || [],
        selected: options.selected || null,
        placeholder: options.placeholder || 'Select an option',
        searchable: options.searchable || false,
        ...options
      },

      show() {
        if (!this.element) this.render();
        this.element.classList.add('show');
        this.dispatchEvent('show');
      },

      hide() {
        if (!this.element) return;
        this.element.classList.remove('show');
        this.dispatchEvent('hide');
      },

      toggle() {
        if (this.element.classList.contains('show')) {
          this.hide();
        } else {
          this.show();
        }
      },

      select(value) {
        this.options.selected = value;
        this.updateDisplay();
        this.hide();
        this.dispatchEvent('select', { value });
      },

      render() {
        const dropdownElement = document.createElement('div');
        dropdownElement.className = 'dropdown';
        dropdownElement.innerHTML = `
          <button class="dropdown-trigger" aria-haspopup="true" aria-expanded="false">
            <span class="dropdown-text"></span>
            <span class="dropdown-arrow">▼</span>
          </button>
          <div class="dropdown-menu" role="menu">
            ${this.options.items.map(item => `
              <div class="dropdown-item" data-value="${item.value}" role="menuitem">
                ${item.label}
              </div>
            `).join('')}
          </div>
        `;

        if (this.options.target) {
          this.options.target.appendChild(dropdownElement);
        } else {
          document.body.appendChild(dropdownElement);
        }

        this.element = dropdownElement;
        this.bindEvents();
        this.updateDisplay();
      },

      bindEvents() {
        const trigger = this.element.querySelector('.dropdown-trigger');
        const menu = this.element.querySelector('.dropdown-menu');

        trigger.addEventListener('click', () => this.toggle());

        // Close on outside click
        document.addEventListener('click', (e) => {
          if (!this.element.contains(e.target)) {
            this.hide();
          }
        });

        // Item selection
        menu.addEventListener('click', (e) => {
          const item = e.target.closest('.dropdown-item');
          if (item) {
            this.select(item.dataset.value);
          }
        });
      },

      updateDisplay() {
        const textElement = this.element?.querySelector('.dropdown-text');
        const trigger = this.element?.querySelector('.dropdown-trigger');

        if (textElement) {
          const selectedItem = this.options.items.find(item => item.value === this.options.selected);
          textElement.textContent = selectedItem ? selectedItem.label : this.options.placeholder;
        }

        if (trigger) {
          trigger.setAttribute('aria-expanded', this.element.classList.contains('show'));
        }
      },

      dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`dropdown:${eventName}`, { detail });
        document.dispatchEvent(event);
      }
    };

    this.components.set(dropdownId, dropdown);
    return dropdown;
  }

  /**
   * Setup global components
   */
  setupGlobalComponents() {
    // Global toast container
    if (!document.querySelector('.toast-container')) {
      const container = document.createElement('div');
      container.className = 'toast-container top-right';
      container.setAttribute('data-position', 'top-right');
      document.body.appendChild(container);
    }

    // Global modal styles
    if (!document.getElementById('global-ui-styles')) {
      const style = document.createElement('style');
      style.id = 'global-ui-styles';
      style.textContent = this.getGlobalStyles();
      document.head.appendChild(style);
    }
  }

  /**
   * Get global UI styles
   * @returns {string} CSS styles
   */
  getGlobalStyles() {
    return `
      /* Modal Styles */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
      }

      .modal-overlay.show {
        opacity: 1;
        visibility: visible;
      }

      .modal-container {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        max-width: 90vw;
        max-height: 90vh;
        overflow: auto;
      }

      .modal-small .modal-container { max-width: 400px; }
      .modal-medium .modal-container { max-width: 600px; }
      .modal-large .modal-container { max-width: 800px; }
      .modal-wide .modal-container { max-width: 1000px; }

      .modal-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
      }

      .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        line-height: 1;
      }

      .modal-body {
        padding: 1.5rem;
      }

      .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
      }

      /* Toast Styles */
      .toast-container {
        position: fixed;
        z-index: 2000;
        pointer-events: none;
      }

      .toast-container.top-right { top: 1rem; right: 1rem; }
      .toast-container.top-left { top: 1rem; left: 1rem; }
      .toast-container.bottom-right { bottom: 1rem; right: 1rem; }
      .toast-container.bottom-left { bottom: 1rem; left: 1rem; }

      .toast {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        margin-bottom: 0.5rem;
        min-width: 300px;
        max-width: 500px;
        opacity: 0;
        transform: translateX(100%);
        transition: opacity 0.3s ease, transform 0.3s ease;
        pointer-events: auto;
      }

      .toast.show {
        opacity: 1;
        transform: translateX(0);
      }

      .toast-success { border-left: 4px solid #10b981; }
      .toast-error { border-left: 4px solid #ef4444; }
      .toast-warning { border-left: 4px solid #f59e0b; }
      .toast-info { border-left: 4px solid #3b82f6; }

      .toast-content {
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .toast-message {
        flex: 1;
        margin-right: 1rem;
      }

      .toast-close {
        background: none;
        border: none;
        font-size: 1.25rem;
        cursor: pointer;
        padding: 0;
        line-height: 1;
      }

      /* Minimal icon button fallback for generic (non-admin) modals */
      .modal-container .btn.btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        cursor: pointer;
      }
      .modal-container .btn-icon--close::before {
        content: '×';
        font-size: 16px;
        line-height: 1;
      }

      /* Spinner Styles */
      .spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
      }

      .spinner.show {
        display: flex;
      }

      .spinner.hide {
        display: none;
      }

      .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        z-index: 3000;
      }

      .spinner-inner {
        width: 40px;
        height: 40px;
        position: relative;
      }

      .spinner-small .spinner-inner { width: 20px; height: 20px; }
      .spinner-large .spinner-inner { width: 60px; height: 60px; }

      .spinner-circle {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 3px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }

      .spinner-circle:nth-child(2) {
        width: 80%;
        height: 80%;
        top: 10%;
        left: 10%;
        animation-direction: reverse;
        opacity: 0.7;
      }

      .spinner-circle:nth-child(3) {
        width: 60%;
        height: 60%;
        top: 20%;
        left: 20%;
        opacity: 0.4;
      }

      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }

      .spinner-text {
        font-size: 0.875rem;
        color: #6b7280;
      }

      /* Dropdown Styles */
      .dropdown {
        position: relative;
        display: inline-block;
      }

      .dropdown-trigger {
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 150px;
      }

      .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
      }

      .dropdown.show .dropdown-menu {
        display: block;
      }

      .dropdown-item {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
      }

      .dropdown-item:last-child {
        border-bottom: none;
      }

      .dropdown-item:hover {
        background: #f9fafb;
      }
    `;
  }

  /**
   * Generate unique ID
   * @param {string} prefix - ID prefix
   * @returns {string} Unique ID
   */
  generateId(prefix) {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Get component by ID
   * @param {string} id - Component ID
   * @returns {Object|null} Component instance
   */
  getComponent(id) {
    return this.components.get(id) || null;
  }

  /**
   * Remove component
   * @param {string} id - Component ID
   */
  removeComponent(id) {
    const component = this.components.get(id);
    if (component && component.destroy) {
      component.destroy();
    }
    this.components.delete(id);
  }

  /**
   * Clean up all components
   */
  cleanup() {
    this.components.forEach((component, id) => {
      this.removeComponent(id);
    });
  }

  /**
   * Show toast notification (convenience method)
   * @param {string} message - Toast message
   * @param {string} type - Toast type
   * @param {Object} options - Toast options
   */
  showToast(message, type = 'info', options = {}) {
    const toast = this.createToast(message, type, options);
    toast.show();
  }

  /**
   * Show loading spinner (convenience method)
   * @param {Object} options - Spinner options
   * @returns {Object} Spinner instance
   */
  showSpinner(options = {}) {
    const spinner = this.createSpinner(options);
    spinner.show();
    return spinner;
  }

  /**
   * Create global UI components instance
   * @param {Object} options - Configuration options
   * @returns {UIComponents} Global UI components
   */
  static createGlobal(options = {}) {
    const components = new UIComponents(options);
    components.init();

    // Make globally available
    window.UIComponents = components;
    window.createModal = (options) => components.createModal(options);
    window.createAdminModal = (options) => components.createAdminModal(options);
    window.showToast = (message, type, options) => components.showToast(message, type, options);
    window.showSpinner = (options) => components.showSpinner(options);

    return components;
  }
}

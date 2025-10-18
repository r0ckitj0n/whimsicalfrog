/**
 * DB Status Display Manager
 * Handles UI rendering for database status information
 */

export class DbStatusDisplayManager {
  constructor(apiTester) {
    this.apiTester = apiTester;
    this.outputContainer = null;
    this.notificationManager = this.createNotificationManager();
  }

  /**
   * Initialize display manager
   * @param {string} outputContainerId - ID of output container element
   */
  init(outputContainerId = 'dbToolsOutput') {
    this.outputContainer = document.getElementById(outputContainerId);
    this.bindEvents();
  }

  /**
   * Display command result
   * @param {Object} result - Command result
   * @param {string} command - Command that was executed
   */
  displayResult(result, command) {
    if (!result) {
      this.displayMessage('No result received');
      return;
    }

    // Handle data commands that should be formatted as JSON
    if (result.data && (command === 'version' || command === 'table_counts' ||
        command === 'db_size' || command === 'list_tables' || command === 'describe')) {
      this.displayJsonData(result.data);
      return;
    }

    // Handle simple message display
    const message = result.message || (result.success ? 'Command executed successfully' : 'Command failed');
    this.displayMessage(message);

    // Handle special cases
    if (result.success && (command === 'test-css' || command === 'generate-css')) {
      this.notificationManager.showSuccess('Operation completed - page will reload');
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    }
  }

  /**
   * Display JSON data in formatted way
   * @param {Object} data - Data to display
   */
  displayJsonData(data) {
    if (!this.outputContainer) return;

    const formatted = JSON.stringify(data, null, 2);
    this.outputContainer.textContent = formatted;

    // Add syntax highlighting if possible
    this.highlightJson();
  }

  /**
   * Display simple message
   * @param {string} message - Message to display
   */
  displayMessage(message) {
    if (!this.outputContainer) return;

    this.outputContainer.textContent = message;
  }

  /**
   * Clear output display
   */
  clearOutput() {
    if (this.outputContainer) {
      this.outputContainer.textContent = '';
    }
  }

  /**
   * Show loading state
   * @param {string} message - Loading message
   */
  showLoading(message = 'Executing command...') {
    if (this.outputContainer) {
      this.outputContainer.innerHTML = `
        <div class="loading-indicator">
          <div class="spinner"></div>
          <span>${message}</span>
        </div>
      `;
    }
  }

  /**
   * Hide loading state
   */
  hideLoading() {
    if (this.outputContainer) {
      const loadingIndicator = this.outputContainer.querySelector('.loading-indicator');
      if (loadingIndicator) {
        loadingIndicator.remove();
      }
    }
  }

  /**
   * Display error message
   * @param {string} error - Error message
   */
  displayError(error) {
    this.displayMessage(`Error: ${error}`);
    this.notificationManager.showError(error);
  }

  /**
   * Display success message
   * @param {string} message - Success message
   */
  displaySuccess(message) {
    this.displayMessage(message);
    this.notificationManager.showSuccess(message);
  }

  /**
   * Render command buttons
   * @param {HTMLElement} container - Container for buttons
   * @param {Array} commands - Array of command configurations
   */
  renderCommandButtons(container, commands) {
    if (!container) return;

    container.innerHTML = commands
      .map(cmd => {
        const description = this.apiTester.getCommandDescription(cmd.command);
        return `
          <button class="btn btn-secondary btn-sm" data-action="runCommand"
                  data-params='${JSON.stringify({ command: cmd.command })}'
                  title="${description}">
            ${cmd.label}
          </button>
        `;
      })
      .join('');
  }

  /**
   * Render status cards
   * @param {HTMLElement} container - Container for status cards
   * @param {Object} statusData - Status data
   */
  renderStatusCards(container, statusData) {
    if (!container || !statusData) return;

    const cards = [
      {
        title: 'Database Version',
        value: statusData.version || 'Unknown',
        icon: '🗄️',
        class: 'version-card'
      },
      {
        title: 'Total Tables',
        value: statusData.tableCount || 0,
        icon: '📊',
        class: 'tables-card'
      },
      {
        title: 'Database Size',
        value: statusData.size || 'Unknown',
        icon: '💾',
        class: 'size-card'
      },
      {
        title: 'Health Status',
        value: statusData.health || 'Unknown',
        icon: statusData.healthy ? '✅' : '❌',
        class: statusData.healthy ? 'healthy-card' : 'error-card'
      }
    ];

    container.innerHTML = cards
      .map(card => `
        <div class="status-card ${card.class}">
          <div class="card-icon">${card.icon}</div>
          <div class="card-content">
            <div class="card-title">${card.title}</div>
            <div class="card-value">${card.value}</div>
          </div>
        </div>
      `)
      .join('');
  }

  /**
   * Render table list
   * @param {HTMLElement} container - Container for table list
   * @param {Array} tables - Array of table names
   */
  renderTableList(container, tables) {
    if (!container || !Array.isArray(tables)) return;

    container.innerHTML = tables.length > 0
      ? tables.map(table => `
          <div class="table-item">
            <span class="table-name">${table}</span>
            <button class="btn btn-sm" data-action="runCommand"
                    data-params='${JSON.stringify({ command: 'describe', table })}'
                    title="Describe table">
              📋
            </button>
          </div>
        `).join('')
      : '<div class="no-data">No tables found</div>';
  }

  /**
   * Update auto-refresh timer display
   * @param {number} secondsLeft - Seconds left until refresh
   */
  updateRefreshTimer(secondsLeft) {
    const timerElement = document.getElementById('refreshTimer');
    if (!timerElement) return;

    timerElement.textContent = `Auto-refresh in ${secondsLeft}s`;

    if (secondsLeft <= 10) {
      timerElement.classList.add('urgent');
    } else {
      timerElement.classList.remove('urgent');
    }
  }

  /**
   * Highlight JSON syntax
   */
  highlightJson() {
    // Simple JSON highlighting - could be enhanced with a library
    const content = this.outputContainer.textContent;
    if (!content) return;

    try {
      const parsed = JSON.parse(content);
      const highlighted = this.simpleJsonHighlight(parsed);
      this.outputContainer.innerHTML = highlighted;
    } catch (e) {
      // Not valid JSON, leave as is
    }
  }

  /**
   * Simple JSON syntax highlighting
   * @param {Object} obj - Object to highlight
   * @returns {string} Highlighted HTML
   */
  simpleJsonHighlight(obj) {
    const json = JSON.stringify(obj, null, 2);

    return json
      .replace(/"([^"]+)":/g, '<span class="json-key">"$1"</span>:')
      .replace(/:\s*"([^"]+)"/g, ': <span class="json-string">"$1"</span>')
      .replace(/:\s*([0-9]+)/g, ': <span class="json-number">$1</span>')
      .replace(/:\s*(true|false)/g, ': <span class="json-boolean">$1</span>')
      .replace(/:\s*(null)/g, ': <span class="json-null">$1</span>');
  }

  /**
   * Bind event handlers
   */
  bindEvents() {
    // Handle command button clicks
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action="runCommand"]');
      if (!btn) return;

      e.preventDefault();

      try {
        const params = btn.dataset.params ? JSON.parse(btn.dataset.params) : {};
        const command = params.command || '';

        if (!this.apiTester.validateCommand(command)) {
          this.notificationManager.showError('Invalid command');
          return;
        }

        this.runCommand(command, params);
      } catch (err) {
        this.notificationManager.showError('Invalid command parameters');
      }
    });
  }

  /**
   * Run a command and display results
   * @param {string} command - Command to run
   * @param {Object} params - Command parameters
   */
  async runCommand(command, params = {}) {
    this.showLoading(`Running ${command}...`);

    try {
      // Update stored parameters
      if (params.env) this.apiTester.setParameters({ env: params.env });
      if (params.table) this.apiTester.setParameters({ table: params.table });

      const result = await this.apiTester.runCommand(command, params);
      this.displayResult(result, command);
    } catch (error) {
      this.displayError(error.message || String(error));
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Create notification manager
   * @returns {Object} Notification manager
   */
  createNotificationManager() {
    return {
      showSuccess: (message) => {
        if (typeof window.showSuccess === 'function') {
          return window.showSuccess(message);
        }
        if (typeof window.showToast === 'function') {
          return window.showToast(message, 'success');
        }
        console.log('[SUCCESS]', message);
      },
      showError: (message) => {
        if (typeof window.showError === 'function') {
          return window.showError(message);
        }
        if (typeof window.showToast === 'function') {
          return window.showToast(message, 'error');
        }
        console.error('[ERROR]', message);
      }
    };
  }
}

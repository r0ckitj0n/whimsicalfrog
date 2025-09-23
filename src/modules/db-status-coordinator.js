/**
 * DB Status Coordinator
 * Main coordinator for database status functionality
 */

import { DbStatusApiTester } from './db-status/api-tester.js';
import { DbStatusDisplayManager } from './db-status/status-display.js';

export class DbStatusCoordinator {
  constructor() {
    this.apiTester = new DbStatusApiTester();
    this.displayManager = new DbStatusDisplayManager(this.apiTester);
    this.autoRefreshInterval = 30000; // 30 seconds
    this.refreshTimer = null;
    this.secondsUntilRefresh = 0;
  }

  /**
   * Initialize the DB status system
   * @param {string} outputContainerId - ID of output container element
   */
  init(outputContainerId = 'dbToolsOutput') {
    // Only run on DB status page
    if (!this.isDbStatusPage()) {
      return;
    }

    console.log('[DbStatusCoordinator] Initializing DB status system...');

    // Initialize display manager
    this.displayManager.init(outputContainerId);

    // Bind events
    this.bindEvents();

    // Set up auto-refresh
    this.setupAutoRefresh();

    console.log('[DbStatusCoordinator] DB status system initialized');
    window.DbStatusCoordinator = this; // Expose for debugging
  }

  /**
   * Check if current page is the DB status page
   * @returns {boolean} True if on DB status page
   */
  isDbStatusPage() {
    const body = document.body;
    const page = (body.dataset && body.dataset.page) || '';
    const path = (body.dataset && body.dataset.path) || window.location.pathname;

    return /db-status/.test(page) || /db_status\.php$/i.test(path) ||
           /admin\/db-status/i.test(page) || document.getElementById('dbToolsOutput') !== null;
  }

  /**
   * Bind event handlers
   */
  bindEvents() {
    // Handle run command button clicks
    document.addEventListener('click', (e) => {
      this.handleRunCommandClick(e);
    });

    // Handle parameter persistence
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action="runCommand"]');
      if (btn && btn.dataset.params) {
        try {
          this.apiTester.setParameters(JSON.parse(btn.dataset.params));
        } catch (error) {
          // Ignore invalid parameters
        }
      }
    });
  }

  /**
   * Handle run command button click
   * @param {Event} event - Click event
   */
  handleRunCommandClick(event) {
    const btn = event.target.closest('[data-action="runCommand"]');
    if (!btn) return;

    event.preventDefault();

    try {
      const params = btn.dataset.params ? JSON.parse(btn.dataset.params) : {};
      const command = params.command || '';

      if (!command) return;

      this.runCommand(command, params);
    } catch (err) {
      this.displayManager.displayError('Invalid command parameters');
    }
  }

  /**
   * Run a database command
   * @param {string} command - Command to run
   * @param {Object} params - Command parameters
   */
  async runCommand(command, params = {}) {
    if (!this.apiTester.validateCommand(command)) {
      this.displayManager.displayError('Invalid command');
      return;
    }

    this.displayManager.showLoading(`Running ${command}...`);

    try {
      // Update stored parameters
      if (params.env) this.apiTester.setParameters({ env: params.env });
      if (params.table) this.apiTester.setParameters({ table: params.table });

      const result = await this.apiTester.runCommand(command, params);
      this.displayManager.displayResult(result, command);
    } catch (error) {
      this.displayManager.displayError(error.message || String(error));
    }
  }

  /**
   * Set up auto-refresh functionality
   */
  setupAutoRefresh() {
    this.secondsUntilRefresh = this.autoRefreshInterval / 1000;

    // Update timer display
    this.refreshTimer = setInterval(() => {
      this.secondsUntilRefresh--;
      this.displayManager.updateRefreshTimer(this.secondsUntilRefresh);

      if (this.secondsUntilRefresh <= 0) {
        this.refreshPage();
      }
    }, 1000);

    // Initial timer display update
    this.displayManager.updateRefreshTimer(this.secondsUntilRefresh);
  }

  /**
   * Refresh the page
   */
  refreshPage() {
    window.location.reload();
  }

  /**
   * Stop auto-refresh
   */
  stopAutoRefresh() {
    if (this.refreshTimer) {
      clearInterval(this.refreshTimer);
      this.refreshTimer = null;
    }
  }

  /**
   * Restart auto-refresh
   */
  restartAutoRefresh() {
    this.stopAutoRefresh();
    this.setupAutoRefresh();
  }

  /**
   * Change auto-refresh interval
   * @param {number} intervalMs - New interval in milliseconds
   */
  setAutoRefreshInterval(intervalMs) {
    this.autoRefreshInterval = intervalMs;
    this.restartAutoRefresh();
  }

  /**
   * Run multiple commands in sequence
   * @param {Array} commands - Array of command names
   * @returns {Promise<Object>} Results object
   */
  async runMultipleCommands(commands) {
    const results = {};

    for (const command of commands) {
      try {
        results[command] = await this.apiTester.runCommand(command);
      } catch (error) {
        results[command] = { error: error.message };
      }
    }

    return results;
  }

  /**
   * Get system status summary
   * @returns {Promise<Object>} Status summary
   */
  async getSystemStatus() {
    try {
      const results = await this.runMultipleCommands([
        'version', 'table_counts', 'db_size', 'health-check'
      ]);

      return {
        version: results.version?.data?.version,
        tableCount: results.table_counts?.data?.total_tables,
        totalRecords: results.table_counts?.data?.total_records,
        databaseSize: results.db_size?.data?.size_formatted,
        healthStatus: results['health-check']?.success ? 'healthy' : 'issues',
        lastChecked: new Date().toISOString()
      };
    } catch (error) {
      console.error('Error getting system status:', error);
      return { error: error.message };
    }
  }

  /**
   * Export status data
   * @returns {Promise<Object>} Export data
   */
  async exportStatusData() {
    try {
      const status = await this.getSystemStatus();
      const parameters = this.apiTester.getParameters();

      return {
        status,
        parameters,
        exportDate: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href
      };
    } catch (error) {
      console.error('Error exporting status data:', error);
      return { error: error.message };
    }
  }

  /**
   * Get command history
   * @returns {Array} Command history
   */
  getCommandHistory() {
    // This would need to be implemented with a storage mechanism
    // For now, return empty array
    return [];
  }

  /**
   * Clear command history
   */
  clearCommandHistory() {
    // This would need to be implemented with a storage mechanism
  }

  /**
   * Test database connectivity
   * @param {string} environment - Environment to test (dev/live)
   * @returns {Promise<Object>} Test result
   */
  async testConnectivity(environment = '') {
    try {
      const result = await this.apiTester.testConnection(environment);
      this.displayManager.displayResult(result, 'test-connection');
      return result;
    } catch (error) {
      this.displayManager.displayError(error.message || 'Connection test failed');
      return { error: error.message };
    }
  }

  /**
   * Run full system diagnostic
   * @returns {Promise<Object>} Diagnostic results
   */
  async runFullDiagnostic() {
    this.displayManager.showLoading('Running full system diagnostic...');

    try {
      const diagnosticCommands = [
        'version', 'table_counts', 'db_size', 'list_tables',
        'health-check', 'check-orphaned', 'system-info'
      ];

      const results = await this.runMultipleCommands(diagnosticCommands);

      // Display summary
      const summary = this.generateDiagnosticSummary(results);
      this.displayManager.displayMessage(summary);

      return results;
    } catch (error) {
      this.displayManager.displayError('Diagnostic failed: ' + error.message);
      return { error: error.message };
    } finally {
      this.displayManager.hideLoading();
    }
  }

  /**
   * Generate diagnostic summary
   * @param {Object} results - Diagnostic results
   * @returns {string} Summary text
   */
  generateDiagnosticSummary(results) {
    let summary = '=== SYSTEM DIAGNOSTIC SUMMARY ===\n\n';

    Object.entries(results).forEach(([command, result]) => {
      summary += `${command.toUpperCase()}:\n`;
      if (result.success) {
        summary += '  ✅ PASSED\n';
        if (result.data) {
          summary += `  Data: ${JSON.stringify(result.data).substring(0, 100)}...\n`;
        }
      } else {
        summary += '  ❌ FAILED\n';
        if (result.message) {
          summary += `  Error: ${result.message}\n`;
        }
      }
      summary += '\n';
    });

    return summary;
  }

  /**
   * Get API tester instance
   * @returns {DbStatusApiTester} API tester instance
   */
  getApiTester() {
    return this.apiTester;
  }

  /**
   * Get display manager instance
   * @returns {DbStatusDisplayManager} Display manager instance
   */
  getDisplayManager() {
    return this.displayManager;
  }
}

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new DbStatusCoordinator().init();
  }, { once: true });
} else {
  new DbStatusCoordinator().init();
}

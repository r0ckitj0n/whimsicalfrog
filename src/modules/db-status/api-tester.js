/**
 * DB Status API Tester
 * Handles API testing logic for database status operations
 */

import { ApiClient } from '../../core/api-client.js';

export class DbStatusApiTester {
  constructor() {
    this.baseUrl = '/api/db_tools.php';
    this.lastParams = {};
  }

  /**
   * Run a database command
   * @param {string} command - Command to run
   * @param {Object} options - Additional options
   * @returns {Promise<Object>} Command result
   */
  async runCommand(command, options = {}) {
    const params = {
      action: command,
      ...this.lastParams,
      ...options
    };

    const queryString = new URLSearchParams(params).toString();

    try {
      // First attempt without CSRF token
      let data = await ApiClient.get(`${this.baseUrl}?${queryString}`);
      // If API returns a structure indicating CSRF required, try with token
      if (data && data.success === false && /csrf/i.test(data.message || data.error || '')) {
        const token = await this.getCsrfToken();
        if (token) {
          data = await ApiClient.get(`${this.baseUrl}?${queryString}`, {}, { headers: { 'X-CSRF-Token': token } });
        }
      }

      // Store parameters for future use
      this.lastParams = params;

      return data;
    } catch (error) {
      console.error('Error running database command:', error);
      throw error;
    }
  }

  /**
   * Get CSRF token
   * @returns {Promise<string|null>} CSRF token or null
   */
  async getCsrfToken() {
    try {
      const data = await ApiClient.get(`${this.baseUrl}?action=csrf_token`);
      return data?.data?.csrf_token || null;
    } catch (error) {
      console.error('Error getting CSRF token:', error);
      return null;
    }
  }

  /**
   * Get database version
   * @returns {Promise<Object>} Version data
   */
  async getVersion() {
    return await this.runCommand('version');
  }

  /**
   * Get table counts
   * @returns {Promise<Object>} Table counts data
   */
  async getTableCounts() {
    return await this.runCommand('table_counts');
  }

  /**
   * Get database size
   * @returns {Promise<Object>} Database size data
   */
  async getDatabaseSize() {
    return await this.runCommand('db_size');
  }

  /**
   * List all tables
   * @returns {Promise<Object>} Tables list data
   */
  async listTables() {
    return await this.runCommand('list_tables');
  }

  /**
   * Describe table structure
   * @param {string} table - Table name
   * @returns {Promise<Object>} Table description data
   */
  async describeTable(table) {
    return await this.runCommand('describe', { table });
  }

  /**
   * Test CSS generation
   * @returns {Promise<Object>} CSS test result
   */
  async testCss() {
    return await this.runCommand('test-css');
  }

  /**
   * Generate CSS
   * @returns {Promise<Object>} CSS generation result
   */
  async generateCss() {
    return await this.runCommand('generate-css');
  }

  /**
   * Test database connection
   * @param {string} env - Environment (dev/live)
   * @returns {Promise<Object>} Connection test result
   */
  async testConnection(env = '') {
    return await this.runCommand('test-connection', { env });
  }

  /**
   * Get system information
   * @returns {Promise<Object>} System info data
   */
  async getSystemInfo() {
    return await this.runCommand('system-info');
  }

  /**
   * Check for orphaned records
   * @returns {Promise<Object>} Orphaned records data
   */
  async checkOrphanedRecords() {
    return await this.runCommand('check-orphaned');
  }

  /**
   * Run health check
   * @returns {Promise<Object>} Health check data
   */
  async runHealthCheck() {
    return await this.runCommand('health-check');
  }

  /**
   * Clear cache
   * @returns {Promise<Object>} Cache clear result
   */
  async clearCache() {
    return await this.runCommand('clear-cache');
  }

  /**
   * Optimize database
   * @returns {Promise<Object>} Optimization result
   */
  async optimizeDatabase() {
    return await this.runCommand('optimize-db');
  }

  /**
   * Backup database
   * @returns {Promise<Object>} Backup result
   */
  async backupDatabase() {
    return await this.runCommand('backup-db');
  }

  /**
   * Restore database
   * @param {string} backupFile - Backup file path
   * @returns {Promise<Object>} Restore result
   */
  async restoreDatabase(backupFile) {
    return await this.runCommand('restore-db', { file: backupFile });
  }

  /**
   * Update parameters for future commands
   * @param {Object} params - Parameters to store
   */
  setParameters(params) {
    this.lastParams = { ...this.lastParams, ...params };
  }

  /**
   * Get current parameters
   * @returns {Object} Current parameters
   */
  getParameters() {
    return { ...this.lastParams };
  }

  /**
   * Clear stored parameters
   */
  clearParameters() {
    this.lastParams = {};
  }

  /**
   * Validate command
   * @param {string} command - Command to validate
   * @returns {boolean} Validation result
   */
  validateCommand(command) {
    const validCommands = [
      'version', 'table_counts', 'db_size', 'list_tables', 'describe',
      'test-css', 'generate-css', 'test-connection', 'system-info',
      'check-orphaned', 'health-check', 'clear-cache', 'optimize-db',
      'backup-db', 'restore-db', 'csrf_token'
    ];

    return validCommands.includes(command);
  }

  /**
   * Get command description
   * @param {string} command - Command name
   * @returns {string} Command description
   */
  getCommandDescription(command) {
    const descriptions = {
      'version': 'Get database version information',
      'table_counts': 'Get record counts for all tables',
      'db_size': 'Get database size information',
      'list_tables': 'List all database tables',
      'describe': 'Show table structure',
      'test-css': 'Test CSS generation',
      'generate-css': 'Generate CSS files',
      'test-connection': 'Test database connection',
      'system-info': 'Get system information',
      'check-orphaned': 'Check for orphaned records',
      'health-check': 'Run system health check',
      'clear-cache': 'Clear system cache',
      'optimize-db': 'Optimize database performance',
      'backup-db': 'Create database backup',
      'restore-db': 'Restore database from backup',
      'csrf_token': 'Get CSRF protection token'
    };

    return descriptions[command] || 'Unknown command';
  }

  /**
   * Format command result for display
   * @param {Object} result - Command result
   * @param {string} command - Command that was run
   * @returns {string} Formatted result
   */
  formatResult(result, command) {
    if (!result) return 'No result';

    if (result.data && (command === 'version' || command === 'table_counts' ||
        command === 'db_size' || command === 'list_tables' || command === 'describe')) {
      return JSON.stringify(result.data, null, 2);
    }

    return result.message || (result.success ? 'Command executed successfully' : 'Command failed');
  }
}

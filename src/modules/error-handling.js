/**
 * Global Error Handling
 * Centralized error handling and logging system
 */

export class ErrorHandler {
  constructor(options = {}) {
    this.logLevel = options.logLevel || 'error'; // error, warn, info, debug
    this.reportErrors = options.reportErrors !== false; // Whether to report errors to external service
    this.errorQueue = [];
    this.maxQueueSize = options.maxQueueSize || 50;
    this.errorListeners = new Set();
    this.initialized = false;
  }

  /**
   * Initialize error handler
   */
  init() {
    if (this.initialized) return;

    this.setupGlobalErrorHandlers();
    this.setupUnhandledRejectionHandler();
    this.setupConsoleOverrides();
    this.initialized = true;

    console.log('[ErrorHandler] Initialized');
  }

  /**
   * Set up global error handlers
   */
  setupGlobalErrorHandlers() {
    window.addEventListener('error', (event) => {
      this.handleError(event.error, {
        type: 'javascript',
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        message: event.message
      });
    });

    window.addEventListener('unhandledrejection', (event) => {
      this.handleError(event.reason, {
        type: 'promise',
        reason: event.reason
      });
    });
  }

  /**
   * Set up unhandled promise rejection handler
   */
  setupUnhandledRejectionHandler() {
    // Already handled by the global error handler above
  }

  /**
   * Override console methods to capture errors
   */
  setupConsoleOverrides() {
    const originalConsoleError = console.error;
    const originalConsoleWarn = console.warn;

    console.error = (...args) => {
      this.log('error', 'Console error:', ...args);
      originalConsoleError.apply(console, args);
    };

    console.warn = (...args) => {
      this.log('warn', 'Console warning:', ...args);
      originalConsoleWarn.apply(console, args);
    };
  }

  /**
   * Handle an error
   * @param {Error} error - Error object
   * @param {Object} context - Error context
   */
  handleError(error, context = {}) {
    const errorInfo = this.processError(error, context);

    // Log the error
    this.logError(errorInfo);

    // Add to queue
    this.addToQueue(errorInfo);

    // Notify listeners
    this.notifyListeners(errorInfo);

    // Report if enabled
    if (this.reportErrors) {
      this.reportError(errorInfo);
    }

    // Show user-friendly message for critical errors
    if (errorInfo.severity === 'critical') {
      this.showUserError(errorInfo);
    }
  }

  /**
   * Process error into structured format
   * @param {Error} error - Error object
   * @param {Object} context - Error context
   * @returns {Object} Processed error info
   */
  processError(error, context = {}) {
    const errorInfo = {
      id: this.generateErrorId(),
      timestamp: new Date().toISOString(),
      message: error?.message || String(error),
      stack: error?.stack || '',
      name: error?.name || 'UnknownError',
      type: context.type || 'unknown',
      severity: this.determineSeverity(error, context),
      context: {
        userAgent: navigator.userAgent,
        url: window.location.href,
        userId: this.getCurrentUserId(),
        sessionId: this.getSessionId(),
        ...context
      },
      handled: true,
      resolved: false
    };

    return errorInfo;
  }

  /**
   * Determine error severity
   * @param {Error} error - Error object
   * @param {Object} context - Error context
   * @returns {string} Error severity
   */
  determineSeverity(error, context) {
    // Critical errors
    if (context.type === 'javascript' && (
      error?.message?.includes('Script error') ||
      error?.message?.includes('Loading chunk')
    )) {
      return 'critical';
    }

    // High severity errors
    if (context.type === 'promise' || error instanceof TypeError) {
      return 'high';
    }

    // Medium severity
    if (error instanceof ReferenceError || error instanceof SyntaxError) {
      return 'medium';
    }

    // Low severity
    return 'low';
  }

  /**
   * Log error
   * @param {Object} errorInfo - Processed error info
   */
  logError(errorInfo) {
    const logData = {
      level: errorInfo.severity,
      message: errorInfo.message,
      context: errorInfo.context,
      stack: errorInfo.stack
    };

    switch (errorInfo.severity) {
      case 'critical':
        console.error('[CRITICAL]', logData);
        break;
      case 'high':
        console.error('[ERROR]', logData);
        break;
      case 'medium':
        console.warn('[WARNING]', logData);
        break;
      case 'low':
        console.warn('[INFO]', logData);
        break;
      default:
        console.log('[LOG]', logData);
    }
  }

  /**
   * Add error to queue
   * @param {Object} errorInfo - Processed error info
   */
  addToQueue(errorInfo) {
    this.errorQueue.push(errorInfo);

    // Maintain queue size
    if (this.errorQueue.length > this.maxQueueSize) {
      this.errorQueue.shift();
    }
  }

  /**
   * Notify error listeners
   * @param {Object} errorInfo - Processed error info
   */
  notifyListeners(errorInfo) {
    this.errorListeners.forEach(listener => {
      try {
        listener(errorInfo);
      } catch (listenerError) {
        console.error('Error in error listener:', listenerError);
      }
    });
  }

  /**
   * Report error to external service
   * @param {Object} errorInfo - Processed error info
   */
  async reportError(errorInfo) {
    try {
      // This would integrate with error reporting services like Sentry, LogRocket, etc.
      // For now, we'll just log it
      console.log('[ERROR REPORT]', errorInfo);

      // Example integration:
      // await ApiClient.post('/api/errors', errorInfo);
    } catch (reportError) {
      console.error('Failed to report error:', reportError);
    }
  }

  /**
   * Show user-friendly error message
   * @param {Object} errorInfo - Processed error info
   */
  showUserError(errorInfo) {
    const message = this.getUserFriendlyMessage(errorInfo);

    if (typeof window.showError === 'function') {
      window.showError(message);
    } else if (typeof window.showToast === 'function') {
      window.showToast(message, 'error');
    } else {
      // Fallback to alert for critical errors
      alert(`An error occurred: ${message}`);
    }
  }

  /**
   * Get user-friendly error message
   * @param {Object} errorInfo - Processed error info
   * @returns {string} User-friendly message
   */
  getUserFriendlyMessage(errorInfo) {
    switch (errorInfo.type) {
      case 'javascript':
        return 'A JavaScript error occurred. Please refresh the page.';
      case 'promise':
        return 'An operation failed. Please try again.';
      default:
        return 'An unexpected error occurred. Please try again.';
    }
  }

  /**
   * Add error listener
   * @param {Function} listener - Error listener function
   */
  addErrorListener(listener) {
    this.errorListeners.add(listener);
  }

  /**
   * Remove error listener
   * @param {Function} listener - Error listener function
   */
  removeErrorListener(listener) {
    this.errorListeners.delete(listener);
  }

  /**
   * Clear error queue
   */
  clearQueue() {
    this.errorQueue = [];
  }

  /**
   * Get error queue
   * @returns {Array} Error queue
   */
  getQueue() {
    return [...this.errorQueue];
  }

  /**
   * Get errors by severity
   * @param {string} severity - Error severity
   * @returns {Array} Errors with specified severity
   */
  getErrorsBySeverity(severity) {
    return this.errorQueue.filter(error => error.severity === severity);
  }

  /**
   * Get error statistics
   * @returns {Object} Error statistics
   */
  getStatistics() {
    const stats = {
      total: this.errorQueue.length,
      bySeverity: {},
      byType: {},
      recent: []
    };

    this.errorQueue.forEach(error => {
      // Count by severity
      stats.bySeverity[error.severity] = (stats.bySeverity[error.severity] || 0) + 1;

      // Count by type
      stats.byType[error.type] = (stats.byType[error.type] || 0) + 1;
    });

    // Get recent errors (last 10)
    stats.recent = this.errorQueue.slice(-10);

    return stats;
  }

  /**
   * Generate unique error ID
   * @returns {string} Error ID
   */
  generateErrorId() {
    return `err_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Get current user ID
   * @returns {string} User ID
   */
  getCurrentUserId() {
    // This would integrate with your authentication system
    return window.currentUserId || 'anonymous';
  }

  /**
   * Get session ID
   * @returns {string} Session ID
   */
  getSessionId() {
    let sessionId = sessionStorage.getItem('error_session_id');
    if (!sessionId) {
      sessionId = `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      sessionStorage.setItem('error_session_id', sessionId);
    }
    return sessionId;
  }

  /**
   * Log message
   * @param {string} level - Log level
   * @param  {...any} args - Log arguments
   */
  log(level, ...args) {
    if (this.shouldLog(level)) {
      const _logData = {
        timestamp: new Date().toISOString(),
        level,
        message: args.join(' ')
      };

      switch (level) {
        case 'debug':
          console.debug('[DEBUG]', ...args);
          break;
        case 'info':
          console.info('[INFO]', ...args);
          break;
        case 'warn':
          console.warn('[WARN]', ...args);
          break;
        case 'error':
          console.error('[ERROR]', ...args);
          break;
        default:
          console.log('[LOG]', ...args);
      }
    }
  }

  /**
   * Check if should log at given level
   * @param {string} level - Log level
   * @returns {boolean} Whether to log
   */
  shouldLog(level) {
    const levels = { debug: 0, info: 1, warn: 2, error: 3 };
    return levels[level] >= levels[this.logLevel];
  }

  /**
   * Set log level
   * @param {string} level - Log level
   */
  setLogLevel(level) {
    if (['debug', 'info', 'warn', 'error'].includes(level)) {
      this.logLevel = level;
    }
  }

  /**
   * Enable error reporting
   */
  enableReporting() {
    this.reportErrors = true;
  }

  /**
   * Disable error reporting
   */
  disableReporting() {
    this.reportErrors = false;
  }

  /**
   * Create global error handler instance
   * @param {Object} options - Configuration options
   * @returns {ErrorHandler} Global error handler
   */
  static createGlobal(options = {}) {
    const handler = new ErrorHandler(options);
    handler.init();

    // Make globally available
    window.ErrorHandler = handler;
    window.handleError = (error, context) => handler.handleError(error, context);
    window.logError = (error, context) => handler.logError(handler.processError(error, context));

    return handler;
  }
}

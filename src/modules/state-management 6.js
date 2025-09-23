/**
 * State Management
 * Centralized application state management with reactivity
 */

export class StateManager {
  constructor(options = {}) {
    this.state = {};
    this.listeners = new Map();
    this.history = [];
    this.maxHistorySize = options.maxHistorySize || 100;
    this.enableHistory = options.enableHistory !== false;
    this.persistence = options.persistence || null; // 'localStorage', 'sessionStorage', or null
    this.persistenceKey = options.persistenceKey || 'app_state';
    this.initialized = false;
  }

  /**
   * Initialize state manager
   */
  init() {
    if (this.initialized) return;

    this.loadPersistedState();
    this.initialized = true;

    console.log('[StateManager] Initialized');
  }

  /**
   * Set state value
   * @param {string} key - State key
   * @param {any} value - State value
   * @param {boolean} silent - Whether to suppress notifications
   */
  set(key, value, silent = false) {
    const oldValue = this.state[key];

    // Don't update if value hasn't changed
    if (JSON.stringify(oldValue) === JSON.stringify(value)) {
      return;
    }

    this.state[key] = value;

    // Add to history
    if (this.enableHistory) {
      this.addToHistory(key, oldValue, value);
    }

    // Persist state
    this.persistState();

    // Notify listeners
    if (!silent) {
      this.notifyListeners(key, value, oldValue);
    }
  }

  /**
   * Get state value
   * @param {string} key - State key
   * @param {any} defaultValue - Default value if key doesn't exist
   * @returns {any} State value or default
   */
  get(key, defaultValue = undefined) {
    return this.state[key] !== undefined ? this.state[key] : defaultValue;
  }

  /**
   * Check if state key exists
   * @param {string} key - State key
   * @returns {boolean} Whether key exists
   */
  has(key) {
    return this.state[key] !== undefined;
  }

  /**
   * Delete state key
   * @param {string} key - State key
   * @param {boolean} silent - Whether to suppress notifications
   */
  delete(key, silent = false) {
    const oldValue = this.state[key];

    if (oldValue === undefined) {
      return;
    }

    delete this.state[key];

    // Add to history
    if (this.enableHistory) {
      this.addToHistory(key, oldValue, undefined);
    }

    // Persist state
    this.persistState();

    // Notify listeners
    if (!silent) {
      this.notifyListeners(key, undefined, oldValue);
    }
  }

  /**
   * Update multiple state values
   * @param {Object} updates - State updates object
   * @param {boolean} silent - Whether to suppress notifications
   */
  setMultiple(updates, silent = false) {
    const oldValues = {};

    // Store old values
    Object.keys(updates).forEach(key => {
      oldValues[key] = this.state[key];
    });

    // Update state
    Object.entries(updates).forEach(([key, value]) => {
      this.state[key] = value;
    });

    // Add to history
    if (this.enableHistory) {
      Object.entries(updates).forEach(([key, value]) => {
        this.addToHistory(key, oldValues[key], value);
      });
    }

    // Persist state
    this.persistState();

    // Notify listeners
    if (!silent) {
      Object.entries(updates).forEach(([key, value]) => {
        this.notifyListeners(key, value, oldValues[key]);
      });
    }
  }

  /**
   * Get all state
   * @returns {Object} All state
   */
  getAll() {
    return { ...this.state };
  }

  /**
   * Clear all state
   * @param {boolean} silent - Whether to suppress notifications
   */
  clear(silent = false) {
    const oldState = { ...this.state };
    this.state = {};

    // Add to history
    if (this.enableHistory) {
      Object.keys(oldState).forEach(key => {
        this.addToHistory(key, oldState[key], undefined);
      });
    }

    // Clear persistence
    this.clearPersistedState();

    // Notify listeners
    if (!silent) {
      Object.keys(oldState).forEach(key => {
        this.notifyListeners(key, undefined, oldState[key]);
      });
    }
  }

  /**
   * Add state change listener
   * @param {string} key - State key to listen for
   * @param {Function} listener - Listener function
   * @param {Object} options - Listener options
   */
  addListener(key, listener, options = {}) {
    if (!this.listeners.has(key)) {
      this.listeners.set(key, new Set());
    }

    const listenerData = {
      listener,
      once: options.once || false,
      immediate: options.immediate !== false
    };

    this.listeners.get(key).add(listenerData);

    // Call immediately if requested and state exists
    if (listenerData.immediate && this.has(key)) {
      const value = this.get(key);
      try {
        listener(value, value, key);
      } catch (error) {
        console.error('Error in immediate state listener:', error);
      }
    }
  }

  /**
   * Remove state change listener
   * @param {string} key - State key
   * @param {Function} listener - Listener function to remove
   */
  removeListener(key, listener) {
    if (!this.listeners.has(key)) return;

    const listeners = this.listeners.get(key);
    for (const listenerData of listeners) {
      if (listenerData.listener === listener) {
        listeners.delete(listenerData);
        break;
      }
    }

    // Clean up empty listener sets
    if (listeners.size === 0) {
      this.listeners.delete(key);
    }
  }

  /**
   * Add one-time listener
   * @param {string} key - State key
   * @param {Function} listener - One-time listener function
   */
  addOneTimeListener(key, listener) {
    this.addListener(key, listener, { once: true });
  }

  /**
   * Wait for state change
   * @param {string} key - State key to wait for
   * @param {Function} predicate - Predicate function
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise} Promise that resolves when condition is met
   */
  waitFor(key, predicate = () => true, timeout = 10000) {
    return new Promise((resolve, reject) => {
      const timeoutId = setTimeout(() => {
        this.removeListener(key, checkListener);
        reject(new Error(`Timeout waiting for state change: ${key}`));
      }, timeout);

      const checkListener = (newValue, oldValue) => {
        try {
          if (predicate(newValue, oldValue)) {
            clearTimeout(timeoutId);
            this.removeListener(key, checkListener);
            resolve(newValue);
          }
        } catch (error) {
          clearTimeout(timeoutId);
          this.removeListener(key, checkListener);
          reject(error);
        }
      };

      // Check current value
      const currentValue = this.get(key);
      try {
        if (predicate(currentValue, currentValue)) {
          clearTimeout(timeoutId);
          resolve(currentValue);
        } else {
          this.addListener(key, checkListener);
        }
      } catch (error) {
        clearTimeout(timeoutId);
        reject(error);
      }
    });
  }

  /**
   * Notify listeners of state change
   * @param {string} key - State key
   * @param {any} newValue - New value
   * @param {any} oldValue - Old value
   */
  notifyListeners(key, newValue, oldValue) {
    if (!this.listeners.has(key)) return;

    const listeners = this.listeners.get(key);
    const listenersToRemove = [];

    listeners.forEach(listenerData => {
      try {
        listenerData.listener(newValue, oldValue, key);

        if (listenerData.once) {
          listenersToRemove.push(listenerData);
        }
      } catch (error) {
        console.error('Error in state listener:', error);
        listenersToRemove.push(listenerData);
      }
    });

    // Remove one-time listeners
    listenersToRemove.forEach(listenerData => {
      listeners.delete(listenerData);
    });

    // Clean up empty listener sets
    if (listeners.size === 0) {
      this.listeners.delete(key);
    }
  }

  /**
   * Add to history
   * @param {string} key - State key
   * @param {any} oldValue - Old value
   * @param {any} newValue - New value
   */
  addToHistory(key, oldValue, newValue) {
    const entry = {
      timestamp: new Date().toISOString(),
      key,
      oldValue,
      newValue
    };

    this.history.push(entry);

    // Maintain history size
    if (this.history.length > this.maxHistorySize) {
      this.history.shift();
    }
  }

  /**
   * Get state history
   * @param {string} key - Optional key filter
   * @returns {Array} State history
   */
  getHistory(key = null) {
    if (key) {
      return this.history.filter(entry => entry.key === key);
    }
    return [...this.history];
  }

  /**
   * Undo last state change
   * @returns {boolean} Success status
   */
  undo() {
    if (this.history.length === 0) return false;

    const lastEntry = this.history.pop();
    this.set(lastEntry.key, lastEntry.oldValue, true);

    return true;
  }

  /**
   * Persist state to storage
   */
  persistState() {
    if (!this.persistence) return;

    try {
      const stateToPersist = JSON.stringify(this.state);
      const storage = this.persistence === 'localStorage' ? localStorage : sessionStorage;
      storage.setItem(this.persistenceKey, stateToPersist);
    } catch (error) {
      console.error('Error persisting state:', error);
    }
  }

  /**
   * Load persisted state
   */
  loadPersistedState() {
    if (!this.persistence) return;

    try {
      const storage = this.persistence === 'localStorage' ? localStorage : sessionStorage;
      const persisted = storage.getItem(this.persistenceKey);

      if (persisted) {
        const parsedState = JSON.parse(persisted);
        this.state = { ...this.state, ...parsedState };
      }
    } catch (error) {
      console.error('Error loading persisted state:', error);
    }
  }

  /**
   * Clear persisted state
   */
  clearPersistedState() {
    if (!this.persistence) return;

    try {
      const storage = this.persistence === 'localStorage' ? localStorage : sessionStorage;
      storage.removeItem(this.persistenceKey);
    } catch (error) {
      console.error('Error clearing persisted state:', error);
    }
  }

  /**
   * Export state data
   * @returns {Object} Export data
   */
  exportState() {
    return {
      state: { ...this.state },
      history: [...this.history],
      exportDate: new Date().toISOString()
    };
  }

  /**
   * Import state data
   * @param {Object} data - State data to import
   */
  importState(data) {
    if (data.state) {
      this.state = { ...data.state };
      this.persistState();
    }

    if (data.history && Array.isArray(data.history)) {
      this.history = [...data.history];
    }
  }

  /**
   * Create computed state
   * @param {string} key - Computed state key
   * @param {Function} computeFn - Compute function
   * @param {Array} dependencies - Dependency keys
   */
  createComputed(key, computeFn, dependencies = []) {
    const updateComputed = () => {
      try {
        const values = dependencies.map(dep => this.get(dep));
        const computedValue = computeFn(...values);
        this.set(key, computedValue, true);
      } catch (error) {
        console.error('Error computing state:', error);
      }
    };

    // Set initial value
    updateComputed();

    // Listen for dependency changes
    dependencies.forEach(dep => {
      this.addListener(dep, updateComputed);
    });
  }

  /**
   * Create derived state
   * @param {string} key - Derived state key
   * @param {Function} deriveFn - Derive function
   * @param {Array} sourceKeys - Source state keys
   */
  createDerived(key, deriveFn, sourceKeys = []) {
    this.createComputed(key, deriveFn, sourceKeys);
  }

  /**
   * Get state statistics
   * @returns {Object} State statistics
   */
  getStatistics() {
    return {
      totalKeys: Object.keys(this.state).length,
      historySize: this.history.length,
      maxHistorySize: this.maxHistorySize,
      listenersCount: this.listeners.size,
      persistenceEnabled: !!this.persistence,
      persistenceType: this.persistence
    };
  }

  /**
   * Create global state manager instance
   * @param {Object} options - Configuration options
   * @returns {StateManager} Global state manager
   */
  static createGlobal(options = {}) {
    const manager = new StateManager(options);
    manager.init();

    // Make globally available
    window.StateManager = manager;
    window.getState = (key, defaultValue) => manager.get(key, defaultValue);
    window.setState = (key, value) => manager.set(key, value);
    window.addStateListener = (key, listener, options) => manager.addListener(key, listener, options);

    return manager;
  }
}

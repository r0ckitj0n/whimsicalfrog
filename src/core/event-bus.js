// WhimsicalFrog Core – EventBus (ES module)
// A very small publish/subscribe system built on the native EventTarget API.
// This replaces various ad-hoc custom event systems found in the legacy code.

export class EventBus {
  constructor() {
    this._target = document.createElement('span'); // lightweight EventTarget
  }

  /**
   * Subscribe to an event.
   * @param {string} type
   * @param {Function} handler
   */
  on(type, handler) {
    this._target.addEventListener(type, handler);
  }

  /**
   * Unsubscribe from an event.
   * @param {string} type
   * @param {Function} handler
   */
  off(type, handler) {
    this._target.removeEventListener(type, handler);
  }

  /**
   * Dispatch an event with optional detail payload.
   * @param {string} type
   * @param {any} detail
   */
  emit(type, detail = null) {
    this._target.dispatchEvent(new CustomEvent(type, { detail }));
  }
}

// Singleton instance for convenience (most code only needs one bus)
export const eventBus = new EventBus();

// Expose globally for legacy bridge during migration
if (typeof window !== 'undefined') {
  window.EventBus = EventBus;
  window.eventBus = eventBus;
}

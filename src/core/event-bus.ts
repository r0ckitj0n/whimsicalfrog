// src/core/EventBus.ts
/**
 * WhimsicalFrog Core – EventBus (TypeScript)
 * A very small publish/subscribe system built on the native EventTarget API.
 */

export class EventBus {
    private _target: HTMLElement;

    constructor() {
        this._target = document.createElement('span');
    }

    /**
     * Subscribe to an event.
     */
    on(type: string, handler: EventListenerOrEventListenerObject): void {
        this._target.addEventListener(type, handler);
    }

    /**
     * Unsubscribe from an event.
     */
    off(type: string, handler: EventListenerOrEventListenerObject): void {
        this._target.removeEventListener(type, handler);
    }

    /**
     * Dispatch an event with optional detail payload.
     */
    emit<T = unknown>(type: string, detail: T | null = null): void {
        this._target.dispatchEvent(new CustomEvent(type, { detail }));
    }
}

// Singleton instance
export const eventBus = new EventBus();

// Expose globally for legacy bridge
if (typeof window !== 'undefined') {
    window.EventBus = EventBus;
    window.eventBus = eventBus;
}

export default eventBus;

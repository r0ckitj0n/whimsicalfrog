// src/room/event-manager.ts
// ES-module for room icon hover/click events and popup wiring.
import logger from '../core/logger.js';
import { extractItemData, getPopupApi, ItemData } from './event-utils.js';

interface WFRoomWindow {
  showGlobalPopup?: (el: HTMLElement, data: ItemData) => void;
  hideGlobalPopup?: () => void;
  scheduleHideGlobalPopup?: (ms: number) => void;
  cancelHideGlobalPopup?: () => void;
  hideGlobalPopupImmediate?: () => void;
  showGlobalItemModal?: (sku: string, data?: ItemData) => void;
  showItemDetailsModal?: (sku: string, data?: ItemData) => void;
}

const DEBUG_EVENTS = (() => {
  try {
    const p = new URLSearchParams(window.location.search || '');
    const fromParam = p.get('wf_diag_events');
    const fromLS = localStorage.getItem('wf_diag_events');
    if (fromParam != null) return fromParam === '1';
    if (fromLS != null) return fromLS === '1';
  } catch { /* URL/localStorage access failed - use default */ }
  return false;
})();

/** Delegated hover / click handlers attached to document once. */
export function attachDelegatedItemEvents(): void {
  if (document.body.hasAttribute('data-wf-room-delegated-listeners')) {
    if (DEBUG_EVENTS) logger.warn('[eventManager] Delegated listeners already attached; skipping duplicate attachment');
    return;
  }
  document.body.setAttribute('data-wf-room-delegated-listeners', 'true');
  if (DEBUG_EVENTS) logger.debug('[eventManager] Delegated listeners attached');

  // Use mouseover/mouseout (bubbling) with guards – works reliably in iframes
  document.addEventListener('mouseover', e => {
    // Guard: ensure event.target is an Element that supports closest()
    const targetEl = e.target as HTMLElement;
    if (!targetEl || typeof targetEl.closest !== 'function') {
      return;
    }
    const overPopup = targetEl.closest('.item-popup');
    const icon = targetEl.closest('.item-icon, .room-item-icon') as HTMLElement;
    // Only cancel hide if pointer is over an icon or the popup itself
    if (overPopup || icon) {
      const { cancelHide } = getPopupApi();
      if (typeof cancelHide === 'function') cancelHide();
    }
    // If not over an icon, do nothing here; mouseout handler controls hide timing.
    if (!icon) {
      if (DEBUG_EVENTS) logger.debug('[eventManager] mouseover on non-icon');
      return;
    }
    if (DEBUG_EVENTS) logger.debug('[eventManager] found icon element:', { icon, className: icon.className });
    const data = extractItemData(icon);
    if (DEBUG_EVENTS) logger.debug('[eventManager] extracted item data:', data);
    const { show } = getPopupApi();
    if (DEBUG_EVENTS) logger.debug('[eventManager] popup function available:', typeof show);
    if (typeof show === 'function' && data) {
      if (DEBUG_EVENTS) logger.debug('[eventManager] calling popup function with:', { icon, data });
      show(icon, data);
      attachPopupPersistence(icon);
      // Optional debug-only fallback reveal attempts
      if (DEBUG_EVENTS) {
        const forceReveal = (attempt: number) => {
          try {
            const p = document.getElementById('itemPopup') as HTMLElement;
            if (!p) return;
            const suppressed = p.classList.contains('suppress-auto-show') || p.dataset.wfGpSuppress === '1';
            const posClass = p.dataset.wfGpPosClass;
            const detailed = document.getElementById('detailedItemModal');
            const detailedVisible = !!(detailed && detailed.getAttribute('aria-hidden') !== 'true' && !(detailed.classList && detailed.classList.contains('hidden')));
            if (suppressed || !posClass || detailedVisible) return;
            if (!p.classList.contains('visible')) {
              try { p.classList.remove('hidden', 'measuring'); } catch { /* DOM manipulation failed */ }
              try { p.classList.add('visible'); } catch { /* DOM manipulation failed */ }
              try { p.setAttribute('aria-hidden', 'false'); } catch { /* DOM manipulation failed */ }
              try { p.classList.add('force-visible'); } catch { /* DOM manipulation failed */ }
              try { logger.debug('[eventManager] fallback reveal applied', { attempt, className: p.className }); } catch { /* Logging failed */ }
            }
          } catch { /* forceReveal failed */ }
        };
        setTimeout(() => forceReveal(1), 0);
      }
    } else {
      if (DEBUG_EVENTS) logger.warn('[eventManager] cannot show popup', { showType: typeof show, hasData: !!data });
    }
  });

  // Schedule hide when leaving icons or popup
  document.addEventListener('mouseout', e => {
    const targetEl = e.target as HTMLElement;
    if (!targetEl || typeof targetEl.closest !== 'function') return;
    const related = e.relatedTarget as HTMLElement;
    const leftIcon = targetEl.closest('.item-icon, .room-item-icon');
    const leftPopup = targetEl.closest('.item-popup');
    if (!leftIcon && !leftPopup) return;
    // If moving into another icon or the popup itself, ignore
    if (related && (related.closest?.('.item-icon, .room-item-icon') || related.closest?.('.item-popup'))) return;
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  });

  document.addEventListener('click', async e => {
    const targetEl = e.target as HTMLElement;
    if (!targetEl || typeof targetEl.closest !== 'function') return;
    // Support clicks on standard icons and any element carrying item data
    const icon = targetEl.closest('.item-icon, .room-item-icon, [data-sku], [data-item], [data-action]') as HTMLElement;
    if (!icon) return;

    // Get the action type from data-action attribute
    const action = icon.dataset?.action || targetEl.dataset?.action;
    if (DEBUG_EVENTS) logger.debug('[eventManager] click action:', action);

    // Handle room shortcuts (data-room navigation)
    const roomTarget = icon.dataset?.room_number || icon.dataset?.room || targetEl.dataset?.room_number || targetEl.dataset?.room;
    if (roomTarget || action === 'openRoom') {
      e.preventDefault();
      const room = roomTarget || icon.dataset?.room || '';
      if (!room) return;
      if (DEBUG_EVENTS) logger.debug('[eventManager] navigating to room:', room);
      if (window.roomModalManager?.show) {
        window.roomModalManager.show(room);
      } else {
        // Fallback: navigate to room page
        window.location.href = `/room_main?room=${room}`;
      }
      return;
    }

    // Handle category navigation
    if (action === 'navigateToCategory') {
      e.preventDefault();
      const categoryId = icon.dataset?.categoryId || '';
      if (DEBUG_EVENTS) logger.debug('[eventManager] navigating to category:', categoryId);
      window.location.href = `/shop?category=${categoryId}`;
      return;
    }

    // Handle modal opening
    if (action === 'openModal') {
      e.preventDefault();
      const modalId = icon.dataset?.modalId || '';
      if (DEBUG_EVENTS) logger.debug('[eventManager] opening modal:', modalId);
      // Try various modal opening mechanisms
      if (modalId === 'cart' && typeof window.openCartModal === 'function') {
        window.openCartModal();
      } else if (modalId === 'login' && typeof window.openLoginModal === 'function') {
        window.openLoginModal();
      } else if (modalId === 'account-settings' && typeof window.openAccountSettings === 'function') {
        window.openAccountSettings();
      } else if (modalId === 'payment' && window.WF_PaymentModal?.open) {
        window.WF_PaymentModal.open();
      } else if (typeof window.showModal === 'function') {
        window.showModal(modalId);
      }
      return;
    }

    // Handle global actions
    if (action && action !== 'openItemModal') {
      e.preventDefault();
      if (DEBUG_EVENTS) logger.debug('[eventManager] executing action:', action);
      // Map common actions to their functions
      if (action === 'open-cart' && typeof window.openCartModal === 'function') {
        window.openCartModal();
      } else if (action === 'open-login' && typeof window.openLoginModal === 'function') {
        window.openLoginModal();
      } else if (action === 'open-account-settings' && typeof window.openAccountSettings === 'function') {
        window.openAccountSettings();
      } else if (action === 'go-back') {
        window.history.back();
      } else if (action === 'go-forward') {
        window.history.forward();
      } else if (action === 'go-home') {
        window.location.href = '/';
      } else if (action === 'go-shop') {
        window.location.href = '/shop';
      } else if (action === 'scroll-to-top') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else if (action === 'refresh-page') {
        window.location.reload();
      } else if (typeof window.performAction === 'function') {
        window.performAction(action);
      }
      return;
    }

    // Default: handle item modal opening
    e.preventDefault();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
    const data = extractItemData(icon);
    const win = window as unknown as WFRoomWindow;
    const par = (parent !== window) ? (parent as unknown as WFRoomWindow) : null;
    const detailsFn =
      window.showItemDetailsModal ||
      window.showGlobalItemModal ||
      window.showDetailedModal;

    if (typeof detailsFn === 'function' && data) {
      try {
        window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate();
      } catch { /* hideGlobalPopupImmediate failed */ }
      detailsFn(data.sku, data);
    }
  });
}

/** For legacy per-icon listeners (called after coordinate positioning). */
export function setupPopupEventsAfterPositioning(): void {
  if (DEBUG_EVENTS) logger.debug('[eventManager] setupPopupEventsAfterPositioning() called');
  // If delegated listeners are active, skip per-icon bindings to avoid duplication
  if (document.body && document.body.hasAttribute('data-wf-room-delegated-listeners')) {
    if (DEBUG_EVENTS) logger.debug('[eventManager] Delegated listeners active; skipping per-icon fallback');
    return;
  }
  const icons = document.querySelectorAll('.item-icon, .room-item-icon') as NodeListOf<HTMLElement>;
  if (DEBUG_EVENTS) logger.warn('[eventManager] Delegation inactive; attaching legacy per-icon listeners to', icons.length, 'icons');
  icons.forEach(icon => {
    // ensure icon is interactive
    icon.classList.add('clickable-icon');
    const data = extractItemData(icon);
    if (!data) return;
    icon.addEventListener('mouseenter', () => {
      const { show, cancelHide } = getPopupApi();
      if (typeof cancelHide === 'function') cancelHide();
      if (typeof show === 'function') show(icon, data);
    });
    icon.addEventListener('mouseleave', () => {
      const { scheduleHide } = getPopupApi();
      if (typeof scheduleHide === 'function') scheduleHide(500);
    });
    icon.addEventListener('click', async e => {
      e.preventDefault();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
      // If this icon is a room shortcut, let navigation handle it
      const roomTarget = icon.dataset?.room_number || icon.dataset?.room;
      if (roomTarget) return;
      const win = window as unknown as WFRoomWindow;
      const par = (parent !== window) ? (parent as unknown as WFRoomWindow) : null;
      let fn =
        window.showItemDetailsModal ||
        window.showGlobalItemModal ||
        window.showDetailedModal;

      if (typeof fn === 'function') {
        try { window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate(); } catch { /* hideGlobalPopupImmediate failed */ }
        fn(data.sku, data);
      }
    });
  });
}

/** Attach mouseenter/leave on icon and popup to keep it visible while hovering either. */
function attachPopupPersistence(icon: HTMLElement): void {
  const popup = document.querySelector('.item-popup') as HTMLElement & { __wfBound?: boolean };
  if (!popup || popup.__wfBound) return;
  popup.__wfBound = true;

  const clearHide = () => {
    const { cancelHide } = getPopupApi();
    if (typeof cancelHide === 'function') cancelHide();
  };

  const scheduleHide = () => {
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  };

  // Bind events
  icon.addEventListener('mouseenter', clearHide);
  icon.addEventListener('mouseleave', scheduleHide);
  popup.addEventListener('mouseenter', clearHide);
  popup.addEventListener('mouseleave', scheduleHide);
}

// Immediately attach delegated listeners on import
if (typeof window !== 'undefined') {
  attachDelegatedItemEvents();
  // Fallback: after initial render, ensure each icon has listeners in case delegation failed (e.g., icons with pointer-events:none)
  setTimeout(() => {
    try {
      setupPopupEventsAfterPositioning();
    } catch (err) {
      logger.warn('[eventManager] setupPopupEventsAfterPositioning failed', err);
    }
  }, 800);

  // Bridge to global for legacy code / iframes
  window.attachDelegatedItemEvents = attachDelegatedItemEvents;
  window.setupPopupEventsAfterPositioning = setupPopupEventsAfterPositioning;
}

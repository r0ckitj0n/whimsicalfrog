import './styles/main.css';

/*
 * Legacy Script Imports for Vite Bundling
 * ----------------------------------------
 * The following scripts were previously part of the legacy js/bundle.js.
 * They are imported here to be included in the modern Vite build process.
 * NOTE: These may need refactoring to proper ES modules to resolve scope issues.
 */
import '../js/whimsical-frog-core.js';
import '../js/utils.js';
import '../js/api-client.js';
import '../js/ui-manager.js';
import '../js/image-viewer.js';
import '../js/global-notifications.js';
import '../js/notification-messages.js';
import '../js/global-popup.js';
import '../js/global-modals.js';
import '../js/modal-functions.js';
import '../js/modal-close-positioning.js';
import '../js/analytics.js';
import '../js/room-coordinate-manager.js';
import '../js/room-functions.js';
import '../js/room-helper.js';
import '../js/room-main.js';
import '../js/global-item-modal.js';
import '../js/detailed-item-modal.js';
import '../js/room-modal-manager.js';
import '../js/cart-system.js';
import '../js/main-application.js';

// WhimsicalFrog ES-module bootstrap (temporary)
// Import migrated modules and re-expose globals for compatibility during the transition.

import {
  handleImageError,
  handleImageErrorSimple,
  setupImageErrorHandling
} from './core/imageErrorHandler.js';

import {
  ApiClient,
  apiGet,
  apiPost,
  apiPut,
  apiDelete
} from './core/apiClient.js';
import './core/utils.js';
import './core/actionRegistry.js';

import { DOMUtils } from './core/domUtils.js';
import { loadDynamicBackground } from './core/dynamicBackground.js';
import './room/roomModalManager.js';
import './room/eventManager.js';
import './commerce/cartSystem.js';
import './commerce/salesChecker.js';
import './ui/searchModal.js';
import './ui/globalPopup.js';
import { EventBus, eventBus } from './core/eventBus.js';

// Legacy global aliases so existing markup keeps working in dev mode
window.handleImageError = handleImageError;
window.handleImageErrorSimple = handleImageErrorSimple;
window.setupImageErrorHandling = setupImageErrorHandling;
window.ApiClient = ApiClient;
window.apiGet = apiGet;
window.apiPost = apiPost;
window.apiPut = apiPut;
window.apiDelete = apiDelete;
window.DOMUtils = DOMUtils;
window.loadDynamicBackground = loadDynamicBackground;

console.log('[WF] ES-module bootstrap loaded');

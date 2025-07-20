import '../css/bundle.css';

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

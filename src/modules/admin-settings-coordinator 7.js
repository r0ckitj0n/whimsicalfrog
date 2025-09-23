// Main Admin Settings Module - Coordinator
// This file coordinates all admin settings functionality
// Individual features are broken out into separate modules for better performance

// Import utilities
import '../modules/utilities.js';

// Import all admin settings sub-modules
import './delegated-handlers.js';
import './modal-managers.js';
import './form-handlers.js';
import './api-handlers.js';
import './initialization.js';

// Global flag to indicate admin settings bridge is loaded
window.__WF_ADMIN_SETTINGS_BRIDGE_INIT = true;

// Export the main admin settings object
export const AdminSettings = {
  // Core functionality exposed to global scope
  init() {
    console.log('[AdminSettings] Initializing...');
    // Initialization handled by individual modules
  }
};

export default AdminSettings;

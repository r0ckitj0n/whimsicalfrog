// Core initialization
async function init() {
    if (WF_CORE.initialized) {
        log('Core already initialized', 'warn');
        return;
    }

    log('Starting WhimsicalFrog Core initialization...');
    
    // Set current page
    const urlParams = new URLSearchParams(window.location.search);
    WF_CORE.state.currentPage = urlParams.get('page') || 'landing';
    
    // Initialize modules
    await initializeModules();
    
    // All modules loaded successfully
    log('All modules initialized successfully');
    
    WF_CORE.initialized = true;
    
    // Emit initialization complete event
    eventBus.emit('core:initialized', WF_CORE);
    
    log('WhimsicalFrog Core initialization complete');
}

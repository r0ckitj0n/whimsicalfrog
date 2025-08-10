// Module initialization
async function initializeModules() {
    try {
        const initOrder = resolveDependencies();
        log(`Initializing modules in order: ${initOrder.join(', ')}`);

        for (const moduleName of initOrder) {
            const module = WF_CORE.modules[moduleName];
            
            if (module.init && typeof module.init === 'function') {
                log(`Initializing module: ${moduleName}`);
                await module.init(WF_CORE);
                module.initialized = true;
                log(`Module initialized: ${moduleName}`);
            } else {
                log(`Module ${moduleName} has no init function`, 'warn');
            }
        }
        
        log('All modules initialized successfully');
        return true;
    } catch (error) {
        log(`Module initialization failed: ${error.message}`, 'error');
        throw error;
    }
}

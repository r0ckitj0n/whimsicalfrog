// Module registration system
function registerModule(name, module) {
    if (WF_CORE.modules[name]) {
        log(`Module ${name} already registered, overwriting`, 'warn');
    }
    
    WF_CORE.modules[name] = {
        ...module,
        initialized: false,
        dependencies: module.dependencies || [],
        priority: module.priority || 0
    };
    
    log(`Module registered: ${name}`);
}

// Dependency resolver
function resolveDependencies() {
    const moduleNames = Object.keys(WF_CORE.modules);
    const resolved = [];
    const resolving = [];

    function resolve(name) {
        if (resolved.includes(name)) return;
        if (resolving.includes(name)) {
            throw new Error(`Circular dependency detected: ${name}`);
        }

        resolving.push(name);
        const module = WF_CORE.modules[name];
        
        if (module.dependencies) {
            module.dependencies.forEach(dep => {
                if (!WF_CORE.modules[dep]) {
                    throw new Error(`Missing dependency: ${dep} for module ${name}`);
                }
                resolve(dep);
            });
        }

        resolving.splice(resolving.indexOf(name), 1);
        resolved.push(name);
    }

    moduleNames.forEach(resolve);
    return resolved;
}

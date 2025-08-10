// WhimsicalFrog reload tracer
// Automatically logs stack traces whenever the page attempts to reload or navigate
// Helps diagnose mysterious refreshes (e.g., popup hover refresh bug)

(function reloadTracer() {
    if (window.__WF_RELOAD_TRACER__) return; // prevent double injection
    window.__WF_RELOAD_TRACER__ = true;

    // Utility to log a labeled stack trace
    function logStack(label) {
        try {
            const err = new Error(label);
            console.group(`%c🔍 ${label}`, 'color: red; font-weight: bold;');
            console.log(err.stack);
            console.groupEnd();
        } catch (e) {
            console.warn('ReloadTracer unable to capture stack:', e);
        }
    }

    // Patch Location.prototype.reload (safer than overriding instance property)
    if (Location && Location.prototype && Location.prototype.reload) {
        const originalReload = Location.prototype.reload;
        Location.prototype.reload = function (...args) {
            logStack('window.location.reload intercepted');
            return originalReload.apply(this, args);
        };
    }

    // Patch Location.prototype.assign / replace for navigation attempts
    ['assign', 'replace'].forEach(fnName => {
        if (Location && Location.prototype && typeof Location.prototype[fnName] === 'function') {
            const originalFn = Location.prototype[fnName];
            Location.prototype[fnName] = function (...args) {
                logStack(`window.location.${fnName} intercepted: ${args[0]}`);
                return originalFn.apply(this, args);
            };
        }
    });

    // Intercept location.href setter
    try {
        const hrefDesc = Object.getOwnPropertyDescriptor(Location.prototype, 'href');
        if (hrefDesc && hrefDesc.set) {
            Object.defineProperty(Location.prototype, 'href', {
                get: hrefDesc.get.bind(window.location),
                set: function(newVal) {
                    logStack(`location.href set → ${newVal}`);
                    hrefDesc.set.call(this, newVal);
                },
                configurable: true,
                enumerable: true
            });
        }
    } catch (e) {
        console.warn('ReloadTracer: unable to patch href setter', e);
    }

    // Persist stack trace across reloads using sessionStorage
    window.addEventListener('beforeunload', () => {
        try {
            sessionStorage.setItem('__WF_LAST_STACK__', new Error('beforeunload trace').stack);
        } catch(e) {}
    });

    window.addEventListener('DOMContentLoaded', () => {
        const last = sessionStorage.getItem('__WF_LAST_STACK__');
        if (last) {
            console.group('%c🔍 Reload cause (previous page):','color: orange;font-weight:bold;');
            console.log(last);
            console.groupEnd();
            sessionStorage.removeItem('__WF_LAST_STACK__');
        }
    });

    console.log('✅ ReloadTracer installed');
})();
